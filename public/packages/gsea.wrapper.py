#!/usr/bin/env python
import os, sys, glob, operator, random, time, subprocess
from multiprocessing import Process, Pool
import multiprocessing
import smtplib, string

#======================start===========================================================================================================================
def run_gsea(cmd):
   os.system(cmd)
   return cmd

if __name__ == '__main__' :

   #======= process the parameters =============================
   cwd = "/users/abcc/hejn/htdocs/onco.sandbox2/public/data/gsea/pub/"
   host = sys.argv[1]
   path = sys.argv[2] 
   for line in open(cwd+path+'/'+path+'.rnk', 'r'):
      if line.find('User')!=-1: email_to = line.split('\t')[1].strip()
      if line.find('java')!=-1: 
         cmd1 = line.split('java')[1].split('-gmx')[0]
         cmd2 = line.split('-collapse')[1] 
         gmx = line.split("'")[1]
         break
   gg = gmx.strip('|').split('|')
   

   #======= prepare gsea jobs ==================== 
   os.chdir('/WWW/htdocs/gsea/pub/')
   os.system('mkdir '+path+'/pub')
   ncpus = 3
   ngset = 3000.0
   cmds = []
   plist = dict()
   links = dict()
   for g in gg: 
      g=g.strip()
      if g.find('WWW')!=-1: 
         lines = open(g, 'r').readlines()
         #change the name of duplicate entries
         for i in range(len(lines)): 
            ss = lines[i].split('\t')
            if ss[0] not in plist : plist[ss[0]]=1
            else: 
               plist[ss[0]]=plist[ss[0]]+1
               lines[i]=lines[i].replace(ss[0], ss[0]+'_'+str(plist[ss[0]]))
         #randomize the entries
         random.shuffle(lines)
         gdir = g[:len(g)-len(g.split('/')[-1])]
         nn = int(len(lines)/ngset)+1
         ll = int(len(lines)/nn)+ 1  
         i = 0 
         for n in range(nn): 
            #split the gmx file into small files
            fn   = '/WWW/htdocs/gsea/pub/'+path+'/pub/'+g[len(gdir):len(g)-len(g.split('.')[-1])]+str(n)+'.'+g.split('.')[-1]
            fp   = open(fn, 'w')
            fp.write(''.join(lines[i:i+ll]))
            fp.close()
            i = i + ll
            #cmd to run the gsea program on each small gmx file
            cmd = "java " + cmd1 + ' -gmx ' + fn + ' -collapse ' + cmd2.strip() + ' >> /WWW/htdocs/gsea/pub/'+path+'/pub/log.'+fn.split('/')[-1] + '  2>&1 '
            cmd = cmd.replace("my_analysis", fn.split('/')[-1])
            fp=open('/WWW/htdocs/gsea/pub/'+path+'/pub/log.'+fn.split('/')[-1], 'w')
            fp.write(cmd+'\n')
            fp.close()
            cmds.append(cmd)
            #os.system(cmd+'&')
            #os.system('rm -rf '+fn)

      elif  g.strip()!='' : 
         cmd = "java " + cmd1 + ' -gmx ' + g + ' -collapse ' + cmd2.strip() + ' >> /WWW/htdocs/gsea/pub/'+path+'/pub/log.'+g.split('/')[-1] + ' 2>&1 '
         cmd = cmd.replace("my_analysis", g.split('/')[-1])
         fp=open('/WWW/htdocs/gsea/pub/'+path+'/pub/log.'+g.split('/')[-1], 'w')
         fp.write(cmd+'\n')
         fp.close()
         cmds.append(cmd)
         #os.system(cmd+'&')
          
   #======== run gsea on the job: parallel computing ==========
   #wait until the system is not used by other program
   #while len(list(res))!=0: time.sleep(5)
   #ps= subprocess.Popen("ps -fuapache | grep python", shell=True, stdout=subprocess.PIPE)
   while len(str(subprocess.Popen("ps -fuapache | grep gsea2", shell=True, stdout=subprocess.PIPE).stdout.read()).split('gsea2'))>4 : 
      time.sleep(5)
   pool = multiprocessing.Pool(ncpus)
   res = pool.imap(run_gsea, cmds)
  
   #======= read transcription factors =======================
   TFs = dict()
   for line in open('/WWW/htdocs/gsea/gene_sets/transcription_factors.txt', 'r'):
      TFs[line.strip()]=1
 
   #======= post processing: merge results files if the genesets file was splited into small files ====================
   os.chdir('/WWW/htdocs/gsea/pub/'+path)
   while len(list(res))!=0: time.sleep(5)
   try: 
      for g in gg: 
         if 1 or g.find('WWW')!=-1: 
            #read the small files
            tags=['for_na_neg', 'for_na_pos']
            for tag in tags: 
               gene = dict()
               gfn = g.split('/')[-1]
               #files = glob.glob('*'+g[len(gdir):len(g)-len(g.split('.')[-1])]+'*.'+g.split('.')[-1]+'*/*'+tag+'*xls')
               files = glob.glob('*'+gfn[:len(gfn)-len(gfn.split('.')[-1])]+'*.'+gfn.split('.')[-1]+'*/*'+tag+'*xls')
               links = glob.glob('*'+gfn[:len(gfn)-len(gfn.split('.')[-1])]+'*.'+gfn.split('.')[-1]+'*/*.html')
               if len(files)>0: 
                  os.system('mkdir '+gfn)
                  gene[tag] = dict()
                  for file in files:
                     for line in open(file, 'r'):
                        if line.find('SCORE')!=-1 : 
                           ntag = 4
                           head = line
                        elif line.find('ES\tNES')!=-1 : 
                           ntag = 5 
                           head = line
                        else: 
                           ss=line.split('\t')
                           gene[tag][line]=float(ss[ntag])
                  #sort the entries and output the data
                  if tag == 'for_na_pos': 
                     sorted_gene = sorted(gene[tag].items(), key=operator.itemgetter(1))
                  if tag == 'for_na_neg': 
                     sorted_gene = sorted(gene[tag].items(), key=operator.itemgetter(1), reverse=True)
                  fp = open(g.split('/')[-1]+'/gsea_report_'+tag+'.tf.xls', 'w')
                  fp.write('Gene\tTranscription Factor\t' + head)
                  for xx in sorted_gene: 
                     #check if the gene is related to transcription factor
                     smbl = ''
                     for tt in TFs.keys(): 
                        if xx[0].find(tt)!=-1: 
                           ss=xx[0].split('_')
                           ok = 0
                           for s in ss: 
                              if s==tt: ok=1
                           if ok==1: smbl=smbl+'|'+tt
                     #output the gene      
                     try: 
                        gsmbl=ss[0].split('WITH ')[1].split('_')[-2]
                     except:
                        gsmbl=xx[0].split('\t')[0]
                     fp.write(gsmbl+'\t')                #add gene symbol to the first column
                     fp.write(smbl.strip('|')+'\t')      #add transcript factor to the second column
                     fp.write(xx[0].strip("\n"))
                     for link in links: 
                        if link.find(xx[0].split('\t')[0])!=-1:
                           fp.write('\t=HYPERLINK("http://'+host+'/gsea/pub/'+path+'/'+link.strip().replace(' ','%20')+'")')
                     fp.write('\n')
                  fp.close()
   except: 
      #ss= "Program met some unknown problem when merge the results. The merged result might not complete." 
      sss= "......" 
   os.chdir('/WWW/htdocs/gsea/pub/')
   os.system('tar -cf '+path+'.tar '+path)
   os.system('zip -r  '+path+'.zip '+path)
   os.system('chmod -R 777 '+path+'.zip '+path+'.tar ')
   os.system('chmod -R 777 /WWW/htdocs/gsea/pub/'+path)
   
   #========send email after job is done=============
   fp=open('/WWW/htdocs/gsea/pub/'+path+'/pub/log.final.email', 'w')
   Subject = 'GSEA Job done: '+path
   From    = "oncogenomics@mail.nih.gov"
   To      = email_to
   Text    = "Your GSEA job is done. Please go to the following link to download the results:\n"
   Text    = Text + "\n(Note: The results will be kept for one months.)\n"
   Text    = Text + "http://"+host+"/gsea/pub/"+path+"\n"
   Text    = Text + "http://"+host+"/gsea/pub/"+path+".tar\n"
   Text    = Text + "http://"+host+"/gsea/pub/"+path+".zip\n"
   Body    = "\r\n".join(("From: %s" % From, "To: %s" % To, "BCC: jianbin.he@gmail.com", "Subject: %s" % Subject , "", Text))
   server = smtplib.SMTP('mailfwd.nih.gov')
   server.sendmail(From, [To], Body)
   server.quit()
   fp.write('sent')
   fp.close()
