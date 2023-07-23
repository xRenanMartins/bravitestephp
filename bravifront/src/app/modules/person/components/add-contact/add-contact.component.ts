import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { MessageService } from 'primeng/api';
import { DialogService, DynamicDialogConfig, DynamicDialogRef } from 'primeng/dynamicdialog';
import { take } from 'rxjs';
import { ContactService } from 'src/app/core/services/contact.service';

@Component({
  selector: 'app-add-contact',
  templateUrl: './add-contact.component.html',
  styleUrls: ['./add-contact.component.scss']
})
export class AddContactComponent implements OnInit{
  form: FormGroup = this.fb.group({
    person_id: [''],
    phone: [''],
    email: ['', [Validators.required, Validators.email]],
    whatsapp: [''],
  });
  item: any;
  person_id: any

  constructor(
    private fb: FormBuilder,
    private contactService: ContactService, 
    public dialogService: DialogService,
    public dynamicDialogRef: DynamicDialogRef,
    private dialogConfig: DynamicDialogConfig,
    private messageService: MessageService,
    
    ){

  }
  ngOnInit(): void {
    if (this.dialogConfig.data) {
      if (this.dialogConfig.data.id) {
        this.person_id = this.dialogConfig.data.id;
      }
      console.log(this.dialogConfig.data)
      if (this.dialogConfig.data.item) {
        this.item = this.dialogConfig.data.item;

        this.form = this.fb.group({
          person_id: [this.item.person_id,Validators.required],
          phone: [this.item.phone],
          email: [this.item.email, [Validators.required, Validators.email]],
          whatsapp: [this.item.whatsapp],
        });
      }
    }
    console.log(this.item);
  }

  
  save(){
    if (!!this.item) this.update();
      else this.create();  
  }

  create(){

    this.form.value.person_id = this.person_id;
    console.log("caiu1")
    
    this.contactService
      .create(this.form.value)
      .pipe(take(1))
      .subscribe(
        (resp: any) => {
          if(resp.success){
            this.messageService.add({severity:'success', summary:'Sucesso', detail:'Contato adicionado com sucesso', life: 3000});
            this.dynamicDialogRef.close(true);
          }
        },
        (err) => {
          // this.isLoading = false;
        }
      );
  }

  update(){
    console.log("caiu2")
    let payload = this.form.value;
    payload.id = this.item.id;

    this.contactService
      .update(payload)
      .pipe(take(1))
      .subscribe(
        (resp: any) => {
          if(resp.success){
            this.messageService.add({severity:'success', summary:'Sucesso', detail:'Contato editado com sucesso', life: 3000});
            this.dynamicDialogRef.close(true);
          }
        },
        (err) => {
          this.messageService.add({severity:'erro', summary:'Erro', detail:'Houve algum problema!!', life: 2000});
        }
      );
  }

}